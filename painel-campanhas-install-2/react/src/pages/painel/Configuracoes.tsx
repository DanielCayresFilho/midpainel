import { useState, useEffect } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, Edit2, Trash2, Database, Link2, CheckCircle, Loader2 } from "lucide-react";
import { PageHeader } from "@/components/layout/PageHeader";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
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
import { Checkbox } from "@/components/ui/checkbox";
import { useToast } from "@/hooks/use-toast";
import { 
  getCarteiras, 
  getCarteira, 
  createCarteira, 
  updateCarteira, 
  deleteCarteira,
  getBasesCarteira,
  vincularBaseCarteira,
  getAvailableBases,
} from "@/lib/api";

interface Carteira {
  id: string;
  nome: string;
  id_carteira: string;
  descricao?: string;
  ativo: number;
  criado_em?: string;
}

export default function Configuracoes() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [isBasesDialogOpen, setIsBasesDialogOpen] = useState(false);
  const [selectedCarteiraId, setSelectedCarteiraId] = useState<string>("");
  const [editingCarteira, setEditingCarteira] = useState<Carteira | null>(null);
  const [formData, setFormData] = useState({ 
    nome: "", 
    id_carteira: "", 
    descricao: "" 
  });
  const [selectedBases, setSelectedBases] = useState<string[]>([]);

  // Buscar carteiras
  const { data: carteiras = [], isLoading } = useQuery({
    queryKey: ['carteiras'],
    queryFn: getCarteiras,
  });

  // Buscar bases disponíveis
  const { data: bases = [] } = useQuery({
    queryKey: ['available-bases'],
    queryFn: async () => {
      const result = await getAvailableBases();
      return Array.isArray(result) ? result : [];
    },
  });

  // Buscar bases vinculadas quando abrir dialog
  const { data: basesCarteira = [] } = useQuery({
    queryKey: ['bases-carteira', selectedCarteiraId],
    queryFn: async () => {
      const result = await getBasesCarteira(selectedCarteiraId);
      return Array.isArray(result) ? result : [];
    },
    enabled: !!selectedCarteiraId && isBasesDialogOpen,
  });

  useEffect(() => {
    if (!isBasesDialogOpen) return;

    if (basesCarteira && Array.isArray(basesCarteira) && basesCarteira.length > 0) {
      const vinculadas = basesCarteira.map((b: any) => {
        // Garante que sempre retorna string
        const nome = b?.nome_base || b?.base || b?.name || b?.id;
        return nome ? String(nome) : null;
      }).filter((v): v is string => Boolean(v));
      setSelectedBases(vinculadas);
    } else {
      // Só seta para vazio se não estiver já vazio
      setSelectedBases(prev => prev.length > 0 ? [] : prev);
    }
  }, [basesCarteira, isBasesDialogOpen]);

  const createMutation = useMutation({
    mutationFn: (data: any) => createCarteira(data),
    onSuccess: () => {
      toast({ title: "Carteira criada com sucesso!" });
      queryClient.invalidateQueries({ queryKey: ['carteiras'] });
      setIsDialogOpen(false);
      setFormData({ nome: "", id_carteira: "", descricao: "" });
      setEditingCarteira(null);
    },
    onError: (error: any) => {
      toast({
        title: "Erro ao criar carteira",
        description: error.message || "Erro ao criar carteira",
        variant: "destructive",
      });
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => updateCarteira(id, data),
    onSuccess: () => {
      toast({ title: "Carteira atualizada com sucesso!" });
      queryClient.invalidateQueries({ queryKey: ['carteiras'] });
      setIsDialogOpen(false);
      setFormData({ nome: "", id_carteira: "", descricao: "" });
      setEditingCarteira(null);
    },
    onError: (error: any) => {
      toast({
        title: "Erro ao atualizar carteira",
        description: error.message || "Erro ao atualizar carteira",
        variant: "destructive",
      });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => deleteCarteira(id),
    onSuccess: () => {
      toast({ title: "Carteira excluída com sucesso!" });
      queryClient.invalidateQueries({ queryKey: ['carteiras'] });
    },
    onError: (error: any) => {
      toast({
        title: "Erro ao excluir carteira",
        description: error.message || "Erro ao excluir carteira",
        variant: "destructive",
      });
    },
  });

  const handleToggleBase = (base: string) => {
    setSelectedBases((prev) =>
      prev.includes(base)
        ? prev.filter((b) => b !== base)
        : [...prev, base]
    );
  };

  const handleSave = () => {
    if (!formData.nome.trim() || !formData.id_carteira.trim()) {
      toast({
        title: "Campos obrigatórios",
        description: "Nome e ID da carteira são obrigatórios",
        variant: "destructive",
      });
      return;
    }

    if (editingCarteira) {
      updateMutation.mutate({ id: editingCarteira.id, data: formData });
    } else {
      createMutation.mutate(formData);
    }
  };

  const vincularMutation = useMutation({
    mutationFn: ({ carteiraId, bases }: { carteiraId: string; bases: string[] }) =>
      vincularBaseCarteira(carteiraId, bases),
    onSuccess: () => {
      toast({ title: "Bases vinculadas com sucesso!" });
      queryClient.invalidateQueries({ queryKey: ['bases-carteira', selectedCarteiraId] });
      setIsBasesDialogOpen(false);
    },
    onError: (error: any) => {
      toast({
        title: "Erro ao vincular bases",
        description: error.message || "Erro ao vincular bases",
        variant: "destructive",
      });
    },
  });

  const handleSaveBases = () => {
    if (!selectedCarteiraId) return;
    vincularMutation.mutate({ carteiraId: selectedCarteiraId, bases: selectedBases });
  };

  const openEdit = async (carteira: Carteira) => {
    try {
      const data = await getCarteira(carteira.id);
      setEditingCarteira(carteira);
      setFormData({
        nome: data.nome || "",
        id_carteira: data.id_carteira || "",
        descricao: data.descricao || "",
      });
      setIsDialogOpen(true);
    } catch (error: any) {
      toast({
        title: "Erro ao carregar carteira",
        description: error.message,
        variant: "destructive",
      });
    }
  };

  const openNew = () => {
    setEditingCarteira(null);
    setFormData({ nome: "", id_carteira: "", descricao: "" });
    setIsDialogOpen(true);
  };

  const openBasesDialog = (carteiraId: string) => {
    setSelectedCarteiraId(carteiraId);
    setIsBasesDialogOpen(true);
  };

  const handleDelete = (id: string) => {
    deleteMutation.mutate(id);
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Configurações"
        description="Gerencie carteiras e vincule bases de dados"
      >
        <Button onClick={openNew} className="gradient-primary hover:opacity-90">
          <Plus className="mr-2 h-4 w-4" />
          Nova Carteira
        </Button>
      </PageHeader>

      {isLoading ? (
        <div className="grid gap-4">
          {[1, 2, 3].map((i) => (
            <Skeleton key={i} className="h-32" />
          ))}
        </div>
      ) : carteiras.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center justify-center py-16">
            <Database className="h-12 w-12 text-muted-foreground mb-4" />
            <h3 className="text-lg font-semibold">Nenhuma carteira cadastrada</h3>
            <p className="text-muted-foreground">Crie sua primeira carteira</p>
          </CardContent>
        </Card>
      ) : (
        <div className="grid gap-4">
          {carteiras.map((carteira: any, index: number) => (
            <Card
              key={carteira.id}
              className="animate-slide-in"
              style={{ animationDelay: `${index * 100}ms` }}
            >
              <CardHeader className="pb-3">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                  <div className="flex items-center gap-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                      <Database className="h-6 w-6 text-primary" />
                    </div>
                    <div>
                      <CardTitle className="text-lg">{carteira.nome}</CardTitle>
                      <CardDescription className="flex items-center gap-2 mt-1">
                        <Badge variant="secondary">ID: {carteira.id_carteira}</Badge>
                        {carteira.ativo ? (
                          <Badge variant="default">Ativa</Badge>
                        ) : (
                          <Badge variant="secondary">Inativa</Badge>
                        )}
                      </CardDescription>
                      {carteira.descricao && (
                        <p className="text-sm text-muted-foreground mt-1">{carteira.descricao}</p>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => openBasesDialog(String(carteira.id))}
                    >
                      <Link2 className="mr-2 h-4 w-4" />
                      Bases
                    </Button>
                    <Button variant="ghost" size="icon" onClick={() => openEdit(carteira)}>
                      <Edit2 className="h-4 w-4" />
                    </Button>
                    <AlertDialog>
                      <AlertDialogTrigger asChild>
                        <Button variant="ghost" size="icon" className="text-destructive hover:text-destructive">
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </AlertDialogTrigger>
                      <AlertDialogContent>
                        <AlertDialogHeader>
                          <AlertDialogTitle>Excluir carteira?</AlertDialogTitle>
                          <AlertDialogDescription>
                            Esta ação não pode ser desfeita. A carteira "{carteira.nome}" será removida permanentemente.
                          </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                          <AlertDialogCancel>Cancelar</AlertDialogCancel>
                          <AlertDialogAction
                            onClick={() => handleDelete(String(carteira.id))}
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
            </Card>
          ))}
        </div>
      )}

      {/* Create/Edit Dialog */}
      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{editingCarteira ? "Editar Carteira" : "Nova Carteira"}</DialogTitle>
            <DialogDescription>
              {editingCarteira
                ? "Atualize as informações da carteira"
                : "Crie uma nova carteira para gerenciar bases"}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="nome">Nome da Carteira <span className="text-red-500">*</span></Label>
              <Input
                id="nome"
                placeholder="Ex: BRADESCO"
                value={formData.nome}
                onChange={(e) => setFormData({ ...formData, nome: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="id_carteira">ID da Carteira <span className="text-red-500">*</span></Label>
              <Input
                id="id_carteira"
                placeholder="Ex: BRD001"
                value={formData.id_carteira}
                onChange={(e) => setFormData({ ...formData, id_carteira: e.target.value })}
              />
              <p className="text-xs text-muted-foreground">
                Este ID será enviado ao provider no lugar de idgis_ambiente
              </p>
            </div>
            <div className="space-y-2">
              <Label htmlFor="descricao">Descrição</Label>
              <Textarea
                id="descricao"
                placeholder="Descrição opcional da carteira"
                value={formData.descricao}
                onChange={(e) => setFormData({ ...formData, descricao: e.target.value })}
                rows={3}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsDialogOpen(false)} disabled={createMutation.isPending || updateMutation.isPending}>
              Cancelar
            </Button>
            <Button
              onClick={handleSave}
              disabled={createMutation.isPending || updateMutation.isPending}
              className="gradient-primary hover:opacity-90"
            >
              {(createMutation.isPending || updateMutation.isPending) && (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              )}
              {editingCarteira ? "Salvar Alterações" : "Criar Carteira"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Bases Dialog */}
      <Dialog open={isBasesDialogOpen} onOpenChange={setIsBasesDialogOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Vincular Bases</DialogTitle>
            <DialogDescription>
              Selecione as bases de dados para vincular a esta carteira
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4 max-h-[400px] overflow-y-auto">
            {!Array.isArray(bases) || bases.length === 0 ? (
              <p className="text-sm text-muted-foreground text-center py-8">
                Nenhuma base disponível
              </p>
            ) : (
              <div className="space-y-2">
                {bases.map((base: any, index: number) => {
                  try {
                    // Garante que todos os valores sejam strings
                    const baseId = base?.id ? String(base.id) : `base-${index}`;
                    const baseName = base?.name ? String(base.name) : base?.id ? String(base.id) : 'Base sem nome';
                    const baseRecords = base?.records ? String(base.records) : null;

                    return (
                      <label
                        key={baseId}
                        className="flex items-center space-x-3 p-3 rounded-lg border cursor-pointer hover:bg-muted/50"
                      >
                        <Checkbox
                          checked={selectedBases.includes(baseName)}
                          onCheckedChange={() => handleToggleBase(baseName)}
                        />
                        <div className="flex-1">
                          <p className="font-medium text-sm">{baseName}</p>
                          {baseRecords && (
                            <p className="text-xs text-muted-foreground">{baseRecords} registros</p>
                          )}
                        </div>
                      </label>
                    );
                  } catch (error) {
                    console.error('Erro ao renderizar base:', base, error);
                    return null;
                  }
                })}
              </div>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsBasesDialogOpen(false)}>
              Cancelar
            </Button>
            <Button onClick={handleSaveBases} className="gradient-primary hover:opacity-90">
              Salvar Vínculos
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
