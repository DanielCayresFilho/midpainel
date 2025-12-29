import { useState, useEffect } from "react";
import { useQuery, useMutation } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { Database, Filter, MessageSquare, Truck, Send, Loader2, AlertCircle } from "lucide-react";
import { PageHeader } from "@/components/layout/PageHeader";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Skeleton } from "@/components/ui/skeleton";
import { useToast } from "@/hooks/use-toast";
import {
  getAvailableBases,
  getFilters,
  getCount,
  getMessages,
  getTemplateContent,
  scheduleCampaign,
  getCarteiras,
  getBasesCarteira,
  checkBaseUpdate,
} from "@/lib/api";

const providers = [
  { id: "CDA", name: "CDA", available: true },
  { id: "GOSAC", name: "GOSAC", available: true },
  { id: "NOAH", name: "NOAH", available: true },
  { id: "RCS", name: "RCS", available: true },
  { id: "SALESFORCE", name: "Salesforce", available: true },
];

export default function NovaCampanha() {
  const navigate = useNavigate();
  const { toast } = useToast();
  const [step, setStep] = useState(1);
  const [selectedFilters, setSelectedFilters] = useState<Record<string, any>>({});
  const [baseUpdateStatus, setBaseUpdateStatus] = useState<{
    isUpdated: boolean;
    message: string;
  } | null>(null);
  const [formData, setFormData] = useState({
    name: "",
    carteira: "",
    base: "",
    template: "",
    templateCode: "",
    templateSource: "",
    message: "",
    providers: [] as string[],
    record_limit: 0,
    exclude_recent_phones: true,
    include_baits: false,
  });

  // Buscar carteiras
  const { data: carteiras = [] } = useQuery({
    queryKey: ['carteiras'],
    queryFn: getCarteiras,
  });

  // Buscar bases da carteira selecionada
  const { data: basesCarteira = [] } = useQuery({
    queryKey: ['bases-carteira', formData.carteira],
    queryFn: () => getBasesCarteira(formData.carteira),
    enabled: !!formData.carteira,
  });

  // Buscar todas as bases disponíveis (fallback se não houver carteira)
  const { data: allBases = [], isLoading: basesLoading } = useQuery({
    queryKey: ['available-bases'],
    queryFn: getAvailableBases,
  });

  // Bases filtradas por carteira
  const bases = formData.carteira
    ? (basesCarteira.length > 0
        ? allBases.filter((base: any) =>
            basesCarteira.some((bc: any) => bc.nome_base === base.name)
          )
        : [])
    : [];

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

  // Buscar filtros quando base for selecionada
  const { data: availableFilters = [], isLoading: filtersLoading } = useQuery({
    queryKey: ['filters', formData.base],
    queryFn: () => getFilters(formData.base),
    enabled: !!formData.base && step >= 2,
  });

  // Calcular contagem quando filtros mudarem
  const { data: recordCount = 0, isLoading: countLoading } = useQuery({
    queryKey: ['count', formData.base, selectedFilters],
    queryFn: () => getCount({
      table_name: formData.base,
      filters: Object.entries(selectedFilters)
        .filter(([_, value]) => value && value !== '' && value !== 'all')
        .map(([key, value]) => ({ column: key, value })),
    }),
    enabled: !!formData.base && step >= 2,
  });

  // Buscar conteúdo do template quando selecionado
  const { data: templateContent, refetch: refetchTemplate } = useQuery({
    queryKey: ['template-content', formData.template],
    queryFn: () => getTemplateContent(formData.template),
    enabled: !!formData.template && step >= 3,
  });

  // Verificar atualização da base quando selecionada
  const { data: baseUpdateData } = useQuery({
    queryKey: ['base-update', formData.base],
    queryFn: () => checkBaseUpdate(formData.base),
    enabled: !!formData.base,
    onSuccess: (data) => {
      setBaseUpdateStatus({
        isUpdated: data.is_updated,
        message: data.message || '',
      });
    },
  });

  // Atualizar mensagem quando template mudar
  useEffect(() => {
    if (formData.template && step === 3) {
      const selectedTemplate = templates.find(t => t.id === formData.template);
      if (selectedTemplate) {
        // Se for template da Ótima, não busca conteúdo local, apenas armazena o código
        if (selectedTemplate.source === 'otima_wpp' || selectedTemplate.source === 'otima_rcs') {
          setFormData(prev => ({ 
            ...prev, 
            templateCode: selectedTemplate.templateCode || '',
            templateSource: selectedTemplate.source || '',
            message: selectedTemplate.name || ''
          }));
        } else if (templateContent?.content) {
          // Template local, busca o conteúdo
          setFormData(prev => ({ ...prev, message: templateContent.content }));
        }
      }
    }
  }, [templateContent, formData.template, step, templates]);

  const scheduleMutation = useMutation({
    mutationFn: (data: any) => scheduleCampaign(data),
    onSuccess: () => {
      toast({
        title: "Campanha criada com sucesso!",
        description: "Sua campanha foi enviada para aprovação.",
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

  const handleProviderToggle = (providerId: string) => {
    setFormData((prev) => ({
      ...prev,
      providers: prev.providers.includes(providerId)
        ? prev.providers.filter((p) => p !== providerId)
        : [...prev.providers, providerId],
    }));
  };

  const handleFilterChange = (column: string, value: any) => {
    setSelectedFilters(prev => ({ ...prev, [column]: value }));
  };

  const handleSubmit = async () => {
    if (!formData.name.trim()) {
      toast({
        title: "Nome obrigatório",
        description: "Por favor, informe o nome da campanha",
        variant: "destructive",
      });
      return;
    }

    if (!formData.base) {
      toast({
        title: "Base obrigatória",
        description: "Por favor, selecione uma base de dados",
        variant: "destructive",
      });
      return;
    }

    if (!formData.template) {
      toast({
        title: "Template obrigatório",
        description: "Por favor, selecione um template de mensagem",
        variant: "destructive",
      });
      return;
    }

    if (formData.providers.length === 0) {
      toast({
        title: "Fornecedor obrigatório",
        description: "Por favor, selecione pelo menos um fornecedor",
        variant: "destructive",
      });
      return;
    }

    // Prepara providers_config (distribuição igual se não especificado)
    const providersConfig: Record<string, number> = {};
    const percentPerProvider = 100 / formData.providers.length;
    formData.providers.forEach(provider => {
      providersConfig[provider] = percentPerProvider;
    });

    const campaignData = {
      table_name: formData.base,
      filters: Object.entries(selectedFilters)
        .filter(([_, value]) => value && value !== '' && value !== 'all')
        .map(([key, value]) => ({ column: key, value })),
      providers_config: providersConfig,
      template_id: formData.templateSource === 'local' ? parseInt(formData.template) : null,
      template_code: formData.templateCode || null,
      template_source: formData.templateSource || 'local',
      record_limit: formData.record_limit || 0,
      exclude_recent_phones: formData.exclude_recent_phones ? 1 : 0,
      include_baits: formData.include_baits ? 1 : 0,
    };

    scheduleMutation.mutate(campaignData);
  };

  const canGoNext = () => {
    switch (step) {
      case 1:
        // Verifica se nome e base estão preenchidos E se a base está atualizada
        const hasRequiredFields = formData.name.trim() && formData.carteira && formData.base;
        const isBaseUpdated = !baseUpdateStatus || baseUpdateStatus.isUpdated;
        return hasRequiredFields && isBaseUpdated;
      case 2:
        return true; // Filtros são opcionais
      case 3:
        return formData.template && formData.message.trim();
      case 4:
        return formData.providers.length > 0;
      default:
        return false;
    }
  };

  // Renderizar filtros dinâmicos
  const renderDynamicFilters = () => {
    if (filtersLoading) {
      return <Skeleton className="h-20" />;
    }

    if (!availableFilters || availableFilters.length === 0) {
      return (
        <Alert>
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>
            Nenhum filtro disponível para esta base
          </AlertDescription>
        </Alert>
      );
    }

    return (
      <div className="grid gap-4 sm:grid-cols-2">
        {availableFilters.map((filter: any) => {
          const filterKey = filter.column || filter.name || filter;
          const filterValue = selectedFilters[filterKey] || '';

          if (filter.type === 'select' || filter.options) {
            const options = filter.options || [];
            return (
              <div key={filterKey} className="space-y-2">
                <Label>{filter.label || filterKey}</Label>
                <Select
                  value={filterValue}
                  onValueChange={(v) => handleFilterChange(filterKey, v)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={`Selecione ${filter.label || filterKey}`} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos</SelectItem>
                    {options.map((opt: any) => (
                      <SelectItem key={opt.value || opt} value={opt.value || opt}>
                        {opt.label || opt}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            );
          }

          return (
            <div key={filterKey} className="space-y-2">
              <Label>{filter.label || filterKey}</Label>
              <Input
                placeholder={`Digite ${filter.label || filterKey}`}
                value={filterValue}
                onChange={(e) => handleFilterChange(filterKey, e.target.value)}
              />
            </div>
          );
        })}
      </div>
    );
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Nova Campanha"
        description="Crie uma nova campanha usando bases de dados"
      />

      {/* Progress Steps */}
      <div className="flex items-center justify-center gap-2">
        {[1, 2, 3, 4].map((s) => (
          <div key={s} className="flex items-center">
            <div
              className={`flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold transition-all ${
                s === step
                  ? "gradient-primary text-primary-foreground shadow-glow"
                  : s < step
                  ? "bg-primary text-primary-foreground"
                  : "bg-muted text-muted-foreground"
              }`}
            >
              {s}
            </div>
            {s < 4 && (
              <div
                className={`h-1 w-12 sm:w-20 mx-2 rounded-full ${
                  s < step ? "bg-primary" : "bg-muted"
                }`}
              />
            )}
          </div>
        ))}
      </div>

      {/* Step Content */}
      <Card className="animate-scale-in">
        {step === 1 && (
          <>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Database className="h-5 w-5 text-primary" />
                Selecionar Base de Dados
              </CardTitle>
              <CardDescription>Escolha a base de dados para sua campanha</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="name">Nome da Campanha</Label>
                <Input
                  id="name"
                  placeholder="Ex: Black Friday 2024"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                />
              </div>

              {/* Seleção de Carteira */}
              <div className="space-y-2">
                <Label>Carteira *</Label>
                <Select
                  value={formData.carteira || undefined}
                  onValueChange={(value) => {
                    setFormData({ ...formData, carteira: value, base: "" });
                  }}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Selecione uma carteira para filtrar as bases" />
                  </SelectTrigger>
                  <SelectContent>
                    {carteiras.map((carteira: any) => (
                      <SelectItem key={carteira.id} value={carteira.id}>
                        {carteira.nome}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {formData.carteira && (
                  <p className="text-xs text-muted-foreground">
                    Mostrando apenas bases vinculadas a esta carteira
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <Label>Base de Dados</Label>
                {!formData.carteira ? (
                  <div className="rounded-xl border-2 border-dashed border-border p-8 text-center">
                    <p className="text-sm text-muted-foreground">
                      Selecione uma carteira para listar as bases disponíveis
                    </p>
                  </div>
                ) : basesLoading ? (
                  <Skeleton className="h-48" />
                ) : bases.length === 0 ? (
                  <div className="rounded-xl border-2 border-dashed border-border p-8 text-center">
                    <p className="text-sm text-muted-foreground">
                      {formData.carteira
                        ? "Nenhuma base vinculada a esta carteira"
                        : "Nenhuma base disponível"}
                    </p>
                  </div>
                ) : (
                  <div className="grid gap-3 sm:grid-cols-3">
                    {bases.map((base: any) => (
                      <button
                        key={base.id}
                        type="button"
                        onClick={() => setFormData({ ...formData, base: base.id })}
                        className={`rounded-xl border-2 p-4 text-left transition-all hover:border-primary/50 ${
                          formData.base === base.id
                            ? "border-primary bg-primary/5"
                            : "border-border"
                        }`}
                      >
                        <p className="font-semibold text-sm">{base.name}</p>
                        <p className="text-xs text-muted-foreground mt-1">{base.records} registros</p>
                      </button>
                    ))}
                  </div>
                )}
              </div>

              {/* Alerta de base desatualizada */}
              {formData.base && baseUpdateStatus && !baseUpdateStatus.isUpdated && (
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
            </CardContent>
          </>
        )}

        {step === 2 && (
          <>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Filter className="h-5 w-5 text-primary" />
                Filtros Avançados
              </CardTitle>
              <CardDescription>Defina os filtros para segmentar sua base (opcional)</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {renderDynamicFilters()}
              <div className="rounded-lg bg-muted/50 p-4">
                <p className="text-sm text-muted-foreground">
                  <span className="font-semibold text-foreground">Estimativa:</span>{" "}
                  {countLoading ? (
                    <Loader2 className="inline h-4 w-4 animate-spin" />
                  ) : (
                    `${recordCount.toLocaleString('pt-BR')} registros após filtros`
                  )}
                </p>
              </div>
            </CardContent>
          </>
        )}

        {step === 3 && (
          <>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <MessageSquare className="h-5 w-5 text-primary" />
                Mensagem
              </CardTitle>
              <CardDescription>Selecione ou crie a mensagem da campanha</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label>Template</Label>
                {templatesLoading ? (
                  <Skeleton className="h-10" />
                ) : (
                  <Select
                    value={formData.template}
                    onValueChange={(v) => {
                      const selectedTemplate = templates.find(t => t.id === v);
                      setFormData({ 
                        ...formData, 
                        template: v,
                        templateCode: selectedTemplate?.templateCode || '',
                        templateSource: selectedTemplate?.source || ''
                      });
                      // Só busca conteúdo se for template local
                      if (selectedTemplate?.source === 'local') {
                        refetchTemplate();
                      }
                    }}
                  >
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
                <Label>Mensagem Personalizada</Label>
                <Textarea
                  placeholder="Digite sua mensagem ou use variáveis como {nome}, {cpf}..."
                  value={formData.message}
                  onChange={(e) => setFormData({ ...formData, message: e.target.value })}
                  rows={5}
                />
                <p className="text-xs text-muted-foreground">
                  Variáveis disponíveis: {"{nome}"}, {"{cpf}"}, {"{telefone}"}, {"{email}"}, {"{link}"}, {"{data}"}
                </p>
              </div>
            </CardContent>
          </>
        )}

        {step === 4 && (
          <>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Truck className="h-5 w-5 text-primary" />
                Fornecedores
              </CardTitle>
              <CardDescription>Selecione os fornecedores para distribuição</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-3 sm:grid-cols-2">
                {providers.map((provider) => (
                  <label
                    key={provider.id}
                    className={`flex items-center gap-3 rounded-xl border-2 p-4 cursor-pointer transition-all ${
                      formData.providers.includes(provider.id)
                        ? "border-primary bg-primary/5"
                        : "border-border hover:border-primary/30"
                    } ${!provider.available && "opacity-50 cursor-not-allowed"}`}
                  >
                    <Checkbox
                      checked={formData.providers.includes(provider.id)}
                      onCheckedChange={() => provider.available && handleProviderToggle(provider.id)}
                      disabled={!provider.available}
                    />
                    <div>
                      <p className="font-semibold">{provider.name}</p>
                      <p className="text-xs text-muted-foreground">
                        {provider.available ? "Disponível" : "Indisponível"}
                      </p>
                    </div>
                  </label>
                ))}
              </div>
              <div className="rounded-lg bg-muted/50 p-4">
                <p className="text-sm text-muted-foreground">
                  <span className="font-semibold text-foreground">Distribuição:</span>{" "}
                  {formData.providers.length > 0
                    ? `Igual entre ${formData.providers.length} fornecedor(es) selecionado(s)`
                    : "Selecione pelo menos um fornecedor"}
                </p>
              </div>

              {/* Opção para incluir iscas */}
              <div className="rounded-lg border-2 border-dashed border-border p-4 space-y-3">
                <div className="flex items-center gap-3">
                  <Checkbox
                    id="include-baits"
                    checked={formData.include_baits}
                    onCheckedChange={(checked) => setFormData({ ...formData, include_baits: !!checked })}
                  />
                  <div className="flex-1">
                    <label htmlFor="include-baits" className="font-semibold cursor-pointer">
                      Incluir iscas de teste
                    </label>
                    <p className="text-xs text-muted-foreground mt-1">
                      Adiciona automaticamente todos os números cadastrados como iscas nesta campanha
                    </p>
                  </div>
                </div>
              </div>
            </CardContent>
          </>
        )}

        {/* Navigation */}
        <CardContent className="flex justify-between border-t pt-6">
          <Button
            variant="outline"
            onClick={() => setStep(Math.max(1, step - 1))}
            disabled={step === 1 || scheduleMutation.isPending}
          >
            Voltar
          </Button>
          {step < 4 ? (
            <Button 
              onClick={() => setStep(step + 1)} 
              disabled={!canGoNext()}
              className="gradient-primary hover:opacity-90"
            >
              Próximo
            </Button>
          ) : (
            <Button
              onClick={handleSubmit}
              disabled={scheduleMutation.isPending || !canGoNext()}
              className="gradient-primary hover:opacity-90"
            >
              {scheduleMutation.isPending ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Criando...
                </>
              ) : (
                <>
                  <Send className="mr-2 h-4 w-4" />
                  Criar Campanha
                </>
              )}
            </Button>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
