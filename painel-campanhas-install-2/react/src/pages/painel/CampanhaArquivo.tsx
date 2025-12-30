import { useState, useEffect } from "react";
import { useQuery, useMutation } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { Upload, FileText, CheckCircle, AlertCircle, Loader2, X } from "lucide-react";
import { PageHeader } from "@/components/layout/PageHeader";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Checkbox } from "@/components/ui/checkbox";
import { useToast } from "@/hooks/use-toast";
import {
  uploadCampaignFile,
  getMessages,
  previewCount,
  createCpfCampaign,
  getAvailableBases,
  getCarteiras,
  getBasesCarteira,
  checkBaseUpdate,
} from "@/lib/api";

const providers = [
  { id: "OTIMA_RCS", name: "Ótima RCS" },
  { id: "OTIMA_WPP", name: "Ótima WPP" },
  { id: "CDA_RCS", name: "CDA RCS" },
  { id: "CDA", name: "CDA" },
  { id: "GOSAC", name: "GOSAC" },
  { id: "NOAH", name: "NOAH" },
  { id: "RCS", name: "RCS" },
  { id: "SALESFORCE", name: "Salesforce" },
];

export default function CampanhaArquivo() {
  const navigate = useNavigate();
  const { toast } = useToast();
  const [file, setFile] = useState<File | null>(null);
  const [tempId, setTempId] = useState<string>("");
  const [matchField, setMatchField] = useState<"cpf" | "telefone">("cpf");
  const [recordCount, setRecordCount] = useState(0);
  const [template, setTemplate] = useState("");
  const [provider, setProvider] = useState("");
  const [carteira, setCarteira] = useState("");
  const [tableName, setTableName] = useState("");
  const [includeBaits, setIncludeBaits] = useState(false);
  const [baseUpdateStatus, setBaseUpdateStatus] = useState<{ isUpdated: boolean; message: string } | null>(null);

  // Buscar templates de mensagem
  const { data: templatesData = [], isLoading: templatesLoading } = useQuery({
    queryKey: ['messages'],
    queryFn: getMessages,
  });

  const templates = templatesData.map((t: any) => ({
    id: String(t.id),
    name: t.title || '',
    source: t.source || 'local',
    templateCode: t.template_code || t.template_id || '',
  }));

  // Buscar carteiras
  const { data: carteiras = [] } = useQuery({
    queryKey: ['carteiras'],
    queryFn: getCarteiras,
  });

  // Buscar bases da carteira selecionada
  const { data: basesCarteira = [] } = useQuery({
    queryKey: ['bases-carteira', carteira],
    queryFn: () => getBasesCarteira(carteira),
    enabled: !!carteira,
  });

  // Buscar todas as bases disponíveis (dados completos)
  const { data: allBases = [], isLoading: basesLoading } = useQuery({
    queryKey: ['available-bases'],
    queryFn: getAvailableBases,
  });

  // Bases filtradas por carteira
  const bases = carteira
    ? (basesCarteira.length > 0
        ? allBases.filter((base: any) =>
            basesCarteira.some((bc: any) => bc.nome_base === base.name)
          )
        : [])
    : [];

  // Verificar atualização da base quando selecionada
  const { data: baseUpdateData } = useQuery({
    queryKey: ['base-update', tableName],
    queryFn: () => checkBaseUpdate(tableName),
    enabled: !!tableName,
  });

  useEffect(() => {
    if (baseUpdateData) {
      setBaseUpdateStatus({
        isUpdated: baseUpdateData.is_updated,
        message: baseUpdateData.message || '',
      });
    }
  }, [baseUpdateData]);

  const uploadMutation = useMutation({
    mutationFn: ({ file, matchField }: { file: File; matchField: string }) => 
      uploadCampaignFile(file, matchField),
    onSuccess: (data: any) => {
      setTempId(data.temp_id);
      setRecordCount(data.count || 0);
      setMatchField(data.match_field || 'cpf');
      toast({
        title: "Arquivo validado com sucesso!",
        description: `${data.count} registros encontrados no arquivo.`,
      });
    },
    onError: (error: any) => {
      toast({
        title: "Erro ao validar arquivo",
        description: error.message || "Erro ao fazer upload do arquivo",
        variant: "destructive",
      });
    },
  });

  const previewMutation = useMutation({
    mutationFn: (data: any) => previewCount(data),
    onSuccess: (data: any) => {
      setRecordCount(data.count || 0);
      toast({
        title: "Preview atualizado",
        description: `${data.count} registros após aplicar filtros.`,
      });
    },
  });

  const createMutation = useMutation({
    mutationFn: (data: any) => createCpfCampaign(data),
    onSuccess: () => {
      toast({
        title: "Campanha criada com sucesso!",
        description: `${recordCount.toLocaleString("pt-BR")} registros serão processados.`,
      });
      navigate("/painel/campanhas");
    },
    onError: (error: any) => {
      toast({
        title: "Erro ao criar campanha",
        description: error.message || "Erro ao criar campanha",
        variant: "destructive",
      });
    },
  });

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = e.target.files?.[0];
    if (!selectedFile) return;

    if (!selectedFile.name.endsWith('.csv')) {
      toast({
        title: "Formato inválido",
        description: "Apenas arquivos CSV são permitidos",
        variant: "destructive",
      });
      return;
    }

    setFile(selectedFile);
    uploadMutation.mutate({ file: selectedFile, matchField });
  };

  const removeFile = () => {
    setFile(null);
    setTempId("");
    setRecordCount(0);
  };

  const handleSubmit = async () => {
    if (!file || !tempId) {
      toast({
        title: "Arquivo obrigatório",
        description: "Por favor, faça upload de um arquivo CSV válido",
        variant: "destructive",
      });
      return;
    }

    if (!template) {
      toast({
        title: "Template obrigatório",
        description: "Por favor, selecione um template de mensagem",
        variant: "destructive",
      });
      return;
    }

    if (!provider) {
      toast({
        title: "Fornecedor obrigatório",
        description: "Por favor, selecione um fornecedor",
        variant: "destructive",
      });
      return;
    }

    if (!carteira) {
      toast({
        title: "Carteira obrigatória",
        description: "Selecione uma carteira para listar a base",
        variant: "destructive",
      });
      return;
    }

    if (!tableName) {
      toast({
        title: "Base obrigatória",
        description: "Por favor, informe o nome da tabela base",
        variant: "destructive",
      });
      return;
    }

    if (baseUpdateStatus && !baseUpdateStatus.isUpdated) {
      toast({
        title: "Base desatualizada",
        description: "Atualize a base antes de criar a campanha",
        variant: "destructive",
      });
      return;
    }

    const selectedTemplate = templates.find((t) => t.id === template);

    createMutation.mutate({
      temp_id: tempId,
      table_name: tableName,
      template_id: selectedTemplate?.source === 'local' ? parseInt(template) : null,
      template_code: selectedTemplate?.templateCode || null,
      template_source: selectedTemplate?.source || 'local',
      provider: provider.toUpperCase(),
      match_field: matchField,
      include_baits: includeBaits ? 1 : 0,
    });
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Campanha via Arquivo"
        description="Crie uma campanha através de upload de arquivo CSV"
      />

      <Alert>
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>
          <strong>Formato do arquivo CSV:</strong> O arquivo deve conter as colunas: <strong>nome</strong>, <strong>telefone</strong> (obrigatório: formato 55 + DDD + Número, ex: 5511999999999), <strong>cpf</strong> (obrigatório: pelo menos 11 dígitos). 
          Colunas opcionais: <strong>carteira</strong>, <strong>contrato</strong>, <strong>id_carteira</strong>.
        </AlertDescription>
      </Alert>

      <div className="grid gap-6 lg:grid-cols-2">
        {/* Upload Section */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Upload className="h-5 w-5 text-primary" />
              Upload de Arquivo
            </CardTitle>
            <CardDescription>
              Envie um arquivo CSV com os dados dos clientes
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {!file ? (
              <label className="flex flex-col items-center justify-center h-48 border-2 border-dashed border-border rounded-xl cursor-pointer hover:border-primary/50 hover:bg-muted/50 transition-colors">
                <Upload className="h-10 w-10 text-muted-foreground mb-3" />
                <p className="text-sm font-medium text-foreground">
                  Clique para selecionar ou arraste o arquivo
                </p>
                <p className="text-xs text-muted-foreground mt-1">
                  CSV com colunas: telefone (55+DDD+Número), cpf, nome (obrigatórios)
                </p>
                <input
                  type="file"
                  accept=".csv"
                  onChange={handleFileChange}
                  className="hidden"
                  disabled={uploadMutation.isPending}
                />
              </label>
            ) : (
              <div className="space-y-4">
                <div className="flex items-center justify-between p-4 rounded-xl bg-muted/50 border">
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                      <FileText className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                      <p className="font-medium text-sm">{file.name}</p>
                      <p className="text-xs text-muted-foreground">
                        {(file.size / 1024).toFixed(1)} KB
                      </p>
                    </div>
                  </div>
                  <Button variant="ghost" size="icon" onClick={removeFile} disabled={uploadMutation.isPending}>
                    <X className="h-4 w-4" />
                  </Button>
                </div>

                {uploadMutation.isPending && (
                  <div className="flex items-center gap-2 text-muted-foreground">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    <span className="text-sm">Validando arquivo...</span>
                  </div>
                )}

                {uploadMutation.isSuccess && tempId && (
                  <div className="flex items-center gap-2 p-3 rounded-lg bg-success/10 text-success">
                    <CheckCircle className="h-4 w-4" />
                    <div className="flex-1">
                      <p className="text-sm font-medium">Arquivo válido!</p>
                      <p className="text-xs">
                        {recordCount.toLocaleString('pt-BR')} registros encontrados
                      </p>
                    </div>
                  </div>
                )}

                {uploadMutation.isError && (
                  <div className="flex items-center gap-2 p-3 rounded-lg bg-destructive/10 text-destructive">
                    <AlertCircle className="h-4 w-4" />
                    <p className="text-sm">
                      {uploadMutation.error instanceof Error 
                        ? uploadMutation.error.message 
                        : "Erro ao validar arquivo"}
                    </p>
                  </div>
                )}
              </div>
            )}

            <div className="space-y-2">
              <Label>Tipo de Cruzamento</Label>
              <Select value={matchField} onValueChange={(v: "cpf" | "telefone") => setMatchField(v)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="cpf">CPF</SelectItem>
                  <SelectItem value="telefone">Telefone</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>

        {/* Configuration Section */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FileText className="h-5 w-5 text-primary" />
              Configuração
            </CardTitle>
            <CardDescription>
              Configure os detalhes da campanha
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Label>Carteira <span className="text-red-500">*</span></Label>
              <Select
                value={carteira || undefined}
                onValueChange={(value) => {
                  setCarteira(value);
                  setTableName("");
                  setBaseUpdateStatus(null);
                }}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione a carteira para filtrar as bases" />
                </SelectTrigger>
                <SelectContent>
                  {carteiras.map((c: any) => (
                    <SelectItem key={c.id} value={c.id}>
                      {c.nome}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <p className="text-xs text-muted-foreground">
                As bases exibidas serão apenas as vinculadas à carteira selecionada
              </p>
            </div>

            <div className="space-y-2">
              <Label htmlFor="table-name">Tabela Base <span className="text-red-500">*</span></Label>
              {!carteira ? (
                <div className="rounded-xl border-2 border-dashed border-border p-4 text-center text-sm text-muted-foreground">
                  Selecione uma carteira para listar as bases disponíveis
                </div>
              ) : basesLoading ? (
                <div className="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-muted-foreground">
                  Carregando bases...
                </div>
              ) : (
                <Select value={tableName} onValueChange={(value) => {
                  setTableName(value);
                  setBaseUpdateStatus(null);
                }}>
                  <SelectTrigger id="table-name">
                    <SelectValue placeholder="Selecione a tabela base" />
                  </SelectTrigger>
                  <SelectContent>
                    {bases.length === 0 ? (
                      <div className="p-3 text-sm text-muted-foreground">
                        Nenhuma base vinculada a esta carteira
                      </div>
                    ) : (
                      bases.map((base: any) => (
                        <SelectItem key={base.id} value={base.id}>
                          {base.name} ({base.records} registros)
                        </SelectItem>
                      ))
                    )}
                  </SelectContent>
                </Select>
              )}
              <p className="text-xs text-muted-foreground">
                Tabela base para cruzamento dos dados do arquivo
              </p>
            </div>

            {tableName && baseUpdateStatus && !baseUpdateStatus.isUpdated && (
              <Alert variant="destructive">
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>
                  <strong>Base desatualizada!</strong> Esta base não foi atualizada hoje.
                  Não é possível criar campanhas com bases desatualizadas.
                  {baseUpdateStatus.message && (
                    <span className="block mt-1 text-xs">{baseUpdateStatus.message}</span>
                  )}
                </AlertDescription>
              </Alert>
            )}

            <div className="space-y-2">
              <Label>Template de Mensagem <span className="text-red-500">*</span></Label>
              {templatesLoading ? (
                <div className="h-10 bg-muted animate-pulse rounded" />
              ) : (
                <Select value={template} onValueChange={setTemplate}>
                  <SelectTrigger>
                    <SelectValue placeholder="Selecione um template" />
                  </SelectTrigger>
                  <SelectContent>
                    {templates.map((t) => (
                      <SelectItem key={t.id} value={t.id}>
                        <div className="flex items-center gap-2">
                          <span>{t.name}</span>
                          {t.source === 'otima_wpp' && (
                            <Badge variant="outline" className="text-xs">Ótima WPP</Badge>
                          )}
                          {t.source === 'otima_rcs' && (
                            <Badge variant="outline" className="text-xs">Ótima RCS</Badge>
                          )}
                        </div>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            </div>

            <div className="space-y-2">
              <Label>Fornecedor <span className="text-red-500">*</span></Label>
              <Select value={provider} onValueChange={setProvider}>
                <SelectTrigger>
                  <SelectValue placeholder="Selecione um fornecedor" />
                </SelectTrigger>
                <SelectContent>
                  {providers.map((p) => (
                    <SelectItem key={p.id} value={p.id}>
                      {p.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {tempId && (
              <div className="rounded-lg bg-muted/50 p-4">
                <p className="text-sm text-muted-foreground">
                  <span className="font-semibold text-foreground">Registros a processar:</span>{" "}
                  {recordCount.toLocaleString("pt-BR")}
                </p>
              </div>
            )}

            {/* Opção para incluir iscas */}
            <div className="rounded-lg border-2 border-dashed border-border p-4 space-y-3">
              <div className="flex items-center gap-3">
                <Checkbox
                  id="include-baits-file"
                  checked={includeBaits}
                  onCheckedChange={(checked) => setIncludeBaits(!!checked)}
                />
                <div className="flex-1">
                  <label htmlFor="include-baits-file" className="font-semibold cursor-pointer">
                    Incluir iscas de teste
                  </label>
                  <p className="text-xs text-muted-foreground mt-1">
                    Adiciona automaticamente todos os números cadastrados como iscas nesta campanha
                  </p>
                </div>
              </div>
            </div>

            <Button
              onClick={handleSubmit}
              disabled={
                !file ||
                !tempId ||
                !template ||
                !provider ||
                !tableName ||
                createMutation.isPending ||
                (baseUpdateStatus && !baseUpdateStatus.isUpdated)
              }
              className="w-full gradient-primary hover:opacity-90"
            >
              {createMutation.isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Criando campanha...
                </>
              ) : (
                <>
                  <FileText className="mr-2 h-4 w-4" />
                  Criar Campanha
                </>
              )}
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
