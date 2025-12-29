import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, Play, Edit2, Trash2, Clock, CheckCircle, Pause, Loader2 } from "lucide-react";
import { PageHeader } from "@/components/layout/PageHeader";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useToast } from "@/hooks/use-toast";
import { 
  getRecurring, 
  saveRecurring, 
  deleteRecurring, 
  toggleRecurring, 
  executeRecurringNow,
  getAvailableBases,
  getMessages,
} from "@/lib/api";

interface RecurringCampaign {
  id: string;
  nome_campanha: string;
  tabela_origem: string;
  filtros_json?: string;
  providers_config: string;
  template_id: string;
  ativo: boolean;
  ultima_execucao?: string;
  totalRuns?: number;
}

export default function CampanhasRecorrentes() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [formData, setFormData] = useState({
    nome_campanha: "",
    table_name: "",
    template_id: "",
    provider: "CDA",
    filters: [] as any[],
    record_limit: 0,
    exclude_recent_phones: true,
  });

  // Buscar campanhas recorrentes
  const { data: campaigns = [], isLoading } = useQuery({
    queryKey: ['recurring-campaigns'],
    queryFn: getRecurring,
  });

  // Buscar bases disponíveis
  const { data: bases = [] } = useQuery({
    queryKey: ['available-bases'],
    queryFn: getAvailableBases,
  });

  // Buscar templates
  const { data: templatesData = [] } = useQuery({
    queryKey: ['messages'],
    queryFn: getMessages,
  });

  const templates = templatesData.map((t: any) => ({
    id: String(t.id),
    name: t.title || '',
  }));

  const saveMutation = useMutation({
    mutationFn: (data: any) => saveRecurring(data),
    onSuccess: () => {
      toast({ title: "Campanha recorrente salva com sucesso!" });
      queryClient.invalidateQueries({ queryKey: ['recurring-campaigns'] });
      setIsDialogOpen(false);
      setFormData({
        nome_campanha: "",
        table_name: "",
        template_id: "",
        provider: "CDA",
        filters: [],
        record_limit: 0,
        exclude_recent_phones: true,
      });
      setEditingId(null);
    },
    onError: (error: any) => {
      toast({
        title: "Erro ao salvar",
        description: error.message || "Erro ao salvar campanha recorrente",
        variant: "destructive",
      });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => deleteRecurring(id),
    onSuccess: () => {
      toast({ title: "Campanha excluída com sucesso!" });
      queryClient.invalidateQueries({ queryKey: ['recurring-campaigns'] });
    },
    onError: (error: any) => {
      toast({
        title: "Erro ao excluir",
        description: error.message || "Erro ao excluir campanha",
        variant: "destructive",
      });
    },
  });

  const toggleMutation = useMutation({
    mutationFn: ({ id, active }: { id: string; active: boolean }) => toggleRecurring(id, active),
    onSuccess: (_, variables) => {
      toast({
        title: variables.active ? "Campanha ativada!" : "Campanha pausada!",
      });
      queryClient.invalidateQueries({ queryKey: ['recurring-campaigns'] });
    },
    onError: (error: any) => {
      toast({
        title: "Erro",
        description: error.message || "Erro ao alterar status",
        variant: "destructive",
      });
    },
  });

  const executeMutation = useMutation({
    mutationFn: (id: string) => executeRecurringNow(id),
    onSuccess: () => {
      toast({
        title: "Execução iniciada",
        description: "A campanha está sendo executada.",
      });
      queryClient.invalidateQueries({ queryKey: ['recurring-campaigns'] });
    },
    onError: (error: any) => {
      toast({
        title: "Erro ao executar",
        description: error.message || "Erro ao executar campanha",
        variant: "destructive",
      });
    },
  });

  const handleToggleActive = (campaign: RecurringCampaign) => {
    toggleMutation.mutate({ id: campaign.id, active: !campaign.ativo });
  };

  const handleExecuteNow = (campaign: RecurringCampaign) => {
    executeMutation.mutate(campaign.id);
  };

  const handleDelete = (id: string) => {
    deleteMutation.mutate(id);
  };

  const handleSave = () => {
    if (!formData.nome_campanha.trim()) {
      toast({
        title: "Nome obrigatório",
        description: "Por favor, informe o nome da campanha",
        variant: "destructive",
      });
      return;
    }

    if (!formData.table_name) {
      toast({
        title: "Base obrigatória",
        description: "Por favor, selecione uma base de dados",
        variant: "destructive",
      });
      return;
    }

    if (!formData.template_id) {
      toast({
        title: "Template obrigatório",
        description: "Por favor, selecione um template",
        variant: "destructive",
      });
      return;
    }

    const providersConfig: Record<string, number> = {
      [formData.provider]: 100,
    };

    saveMutation.mutate({
      nome_campanha: formData.nome_campanha,
      table_name: formData.table_name,
      template_id: parseInt(formData.template_id),
      providers_config: providersConfig,
      filters: formData.filters,
      record_limit: formData.record_limit || 0,
      exclude_recent_phones: formData.exclude_recent_phones ? 1 : 0,
      id: editingId ? parseInt(editingId) : undefined,
    });
  };

  const openNewDialog = () => {
    setEditingId(null);
    setFormData({
      nome_campanha: "",
      table_name: "",
      template_id: "",
      provider: "CDA",
      filters: [],
      record_limit: 0,
      exclude_recent_phones: true,
    });
    setIsDialogOpen(true);
  };

  const formatDate = (dateString?: string) => {
    if (!dateString || dateString === '0000-00-00 00:00:00') return '-';
    try {
      return new Date(dateString).toLocaleString('pt-BR');
    } catch {
      return '-';
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Campanhas Recorrentes"
        description="Gerencie templates de campanhas automáticas"
      >
        <Button onClick={openNewDialog} className="gradient-primary hover:opacity-90">
          <Plus className="mr-2 h-4 w-4" />
          Nova Recorrente
        </Button>
      </PageHeader>

      {isLoading ? (
        <div className="grid gap-4">
          {[1, 2, 3].map((i) => (
            <Skeleton key={i} className="h-48" />
          ))}
        </div>
      ) : campaigns.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center justify-center py-16">
            <Clock className="h-12 w-12 text-muted-foreground mb-4" />
            <h3 className="text-lg font-semibold">Nenhuma campanha recorrente</h3>
            <p className="text-muted-foreground">Crie sua primeira campanha recorrente</p>
          </CardContent>
        </Card>
      ) : (
        <div className="grid gap-4">
          {campaigns.map((campaign: any, index: number) => (
            <Card
              key={campaign.id}
              className="animate-slide-in"
              style={{ animationDelay: `${index * 100}ms` }}
            >
              <CardHeader className="pb-3">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                  <div className="flex items-start gap-4">
                    <div
                      className={`flex h-12 w-12 items-center justify-center rounded-xl ${
                        campaign.ativo ? "bg-success/10" : "bg-muted"
                      }`}
                    >
                      {campaign.ativo ? (
                        <CheckCircle className="h-6 w-6 text-success" />
                      ) : (
                        <Pause className="h-6 w-6 text-muted-foreground" />
                      )}
                    </div>
                    <div>
                      <CardTitle className="text-lg">{campaign.nome_campanha}</CardTitle>
                      <CardDescription className="flex flex-wrap items-center gap-2 mt-1">
                        <Badge variant="secondary">{campaign.tabela_origem}</Badge>
                        <span>•</span>
                        <span>{JSON.parse(campaign.providers_config || '{}')}</span>
                      </CardDescription>
                    </div>
                  </div>
                  <div className="flex items-center gap-3">
                    <Switch
                      checked={campaign.ativo}
                      onCheckedChange={() => handleToggleActive(campaign)}
                      disabled={toggleMutation.isPending}
                    />
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleExecuteNow(campaign)}
                      disabled={!campaign.ativo || executeMutation.isPending}
                    >
                      {executeMutation.isPending ? (
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                      ) : (
                        <Play className="mr-2 h-4 w-4" />
                      )}
                      Executar Agora
                    </Button>
                    <AlertDialog>
                      <AlertDialogTrigger asChild>
                        <Button variant="ghost" size="icon" className="text-destructive hover:text-destructive">
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </AlertDialogTrigger>
                      <AlertDialogContent>
                        <AlertDialogHeader>
                          <AlertDialogTitle>Excluir campanha recorrente?</AlertDialogTitle>
                          <AlertDialogDescription>
                            Esta ação não pode ser desfeita. A campanha "{campaign.nome_campanha}" será removida permanentemente.
                          </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                          <AlertDialogCancel>Cancelar</AlertDialogCancel>
                          <AlertDialogAction
                            onClick={() => handleDelete(String(campaign.id))}
                            className="bg-destructive hover:bg-destructive/90"
                            disabled={deleteMutation.isPending}
                          >
                            {deleteMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Excluir
                          </AlertDialogAction>
                        </AlertDialogFooter>
                      </AlertDialogContent>
                    </AlertDialog>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div className="grid gap-4 sm:grid-cols-4">
                  <div className="rounded-lg bg-muted/50 p-3">
                    <p className="text-xs text-muted-foreground mb-1">Base de Dados</p>
                    <p className="font-medium text-sm">{campaign.tabela_origem}</p>
                  </div>
                  <div className="rounded-lg bg-muted/50 p-3">
                    <p className="text-xs text-muted-foreground mb-1">Template ID</p>
                    <p className="font-medium text-sm">{campaign.template_id}</p>
                  </div>
                  <div className="rounded-lg bg-muted/50 p-3">
                    <p className="text-xs text-muted-foreground mb-1 flex items-center gap-1">
                      <Clock className="h-3 w-3" /> Última Execução
                    </p>
                    <p className="font-medium text-sm">{formatDate(campaign.ultima_execucao)}</p>
                  </div>
                  <div className="rounded-lg bg-muted/50 p-3">
                    <p className="text-xs text-muted-foreground mb-1">Status</p>
                    <Badge variant={campaign.ativo ? "default" : "secondary"}>
                      {campaign.ativo ? "Ativa" : "Inativa"}
                    </Badge>
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Create/Edit Dialog */}
      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{editingId ? "Editar Campanha Recorrente" : "Nova Campanha Recorrente"}</DialogTitle>
            <DialogDescription>Configure uma campanha automática</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label>Nome da Campanha</Label>
              <Input
                placeholder="Ex: Aniversariantes Diários"
                value={formData.nome_campanha}
                onChange={(e) => setFormData({ ...formData, nome_campanha: e.target.value })}
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Fornecedor</Label>
                <Select
                  value={formData.provider}
                  onValueChange={(v) => setFormData({ ...formData, provider: v })}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="CDA">CDA</SelectItem>
                    <SelectItem value="GOSAC">GOSAC</SelectItem>
                    <SelectItem value="NOAH">NOAH</SelectItem>
                    <SelectItem value="RCS">RCS</SelectItem>
                    <SelectItem value="SALESFORCE">Salesforce</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>Limite de Registros</Label>
                <Input
                  type="number"
                  placeholder="0 = ilimitado"
                  value={formData.record_limit}
                  onChange={(e) => setFormData({ ...formData, record_limit: parseInt(e.target.value) || 0 })}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label>Base de Dados</Label>
              <Select
                value={formData.table_name}
                onValueChange={(v) => setFormData({ ...formData, table_name: v })}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione" />
                </SelectTrigger>
                <SelectContent>
                  {bases.map((base: any) => (
                    <SelectItem key={base.id} value={base.id}>
                      {base.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>Template</Label>
              <Select
                value={formData.template_id}
                onValueChange={(v) => setFormData({ ...formData, template_id: v })}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Selecione" />
                </SelectTrigger>
                <SelectContent>
                  {templates.map((t) => (
                    <SelectItem key={t.id} value={t.id}>
                      {t.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsDialogOpen(false)} disabled={saveMutation.isPending}>
              Cancelar
            </Button>
            <Button
              onClick={handleSave}
              disabled={saveMutation.isPending}
              className="gradient-primary hover:opacity-90"
            >
              {saveMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Salvar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
